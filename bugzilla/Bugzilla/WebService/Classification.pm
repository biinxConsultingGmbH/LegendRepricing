# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.
#
# This Source Code Form is "Incompatible With Secondary Licenses", as
# defined by the Mozilla Public License, v. 2.0.

package Bugzilla::WebService::Classification;

use strict;

use base qw (Bugzilla::WebService);

use Bugzilla::Classification;
use Bugzilla::Error;
use Bugzilla::WebService::Util qw(filter validate params_to_objects);

use constant READ_ONLY => qw(
    get
);

sub get {
    my ($self, $params) = validate(@_, 'names', 'ids');

    defined $params->{names} || defined $params->{ids}
        || ThrowCodeError('params_required', { function => 'Classification.get',
                                               params => ['names', 'ids'] });

    my $user = Bugzilla->user;

    Bugzilla->params->{'useclassification'}
      || $user->in_group('editclassifications')
      || ThrowUserError('auth_classification_not_enabled');

    Bugzilla->switch_to_shadow_db;

    my @classification_objs = @{ params_to_objects($params, 'Bugzilla::Classification') };
    unless ($user->in_group('editclassifications')) {
        my %selectable_class = map { $_->id => 1 } @{$user->get_selectable_classifications};
        @classification_objs = grep { $selectable_class{$_->id} } @classification_objs;
    }

    my @classifications = map { filter($params, $self->_classification_to_hash($_)) } @classification_objs;

    return { classifications => \@classifications };
}

sub _classification_to_hash {
    my ($self, $classification) = @_;

    my $user = Bugzilla->user;
    return unless (Bugzilla->params->{'useclassification'} || $user->in_group('editclassifications'));

    my $products = $user->in_group('editclassifications') ?
                     $classification->products : $user->get_selectable_products($classification->id);
    my %hash = (
        id          => $self->type('int',    $classification->id),
        name        => $self->type('string', $classification->name),
        description => $self->type('string', $classification->description),
        sort_key    => $self->type('int',    $classification->sortkey),
        products    => [ map { $self->_product_to_hash($_) } @$products ],
    );

    return \%hash;
}

sub _product_to_hash {
    my ($self, $product) = @_;
    my %hash = (
       id          => $self->type('int', $product->id),
       name        => $self->type('string', $product->name),
       description => $self->type('string', $product->description),
   );

   return \%hash;
}

1;

__END__

=head1 NAME

Bugzilla::Webservice::Classification - The Classification API

=head1 DESCRIPTION

This part of the Bugzilla API allows you to deal with the available Classifications. 
You will be able to get information about them as well as manipulate them.

=head1 METHODS

See L<Bugzilla::WebService> for a description of how parameters are passed,
and what B<STABLE>, B<UNSTABLE>, and B<EXPERIMENTAL> mean.

=head1 Classification Retrieval

=head2 get

B<EXPERIMENTAL>

=over

=item B<Description>

Returns a hash containing information about a set of classifications.

=item B<Params>

In addition to the parameters below, this method also accepts the
standard L<include_fields|Bugzilla::WebService/include_fields> and
L<exclude_fields|Bugzilla::WebService/exclude_fields> arguments.

You could get classifications info by supplying their names and/or ids. 
So, this method accepts the following parameters:

=over

=item C<ids>

An array of classification ids.

=item C<names>

An array of classification names.

=back

=item B<Returns>    

A hash with the key C<classifications> and an array of hashes as the corresponding value.
Each element of the array represents a classification that the user is authorized to see 
and has the following keys:

=over

=item C<id>

C<int> The id of the classification.

=item C<name>

C<string> The name of the classification.

=item C<description>

C<string> The description of the classificaion.

=item C<sort_key>

C<int> The value which determines the order the classification is sorted.

=item C<products>

An array of hashes. The array contains the products the user is authorized to
access within the classification. Each hash has the following keys:

=over

=item C<name>

C<string> The name of the product.

=item C<id>

C<int> The id of the product.

=item C<description>

C<string> The description of the product.

=back

=back

=item B<Errors>

=over

=item 900 (Classification not enabled)

Classification is not enabled on this installation.

=back

=item B<History>

=over

=item Added in Bugzilla B<4.4>.

=back

=back

